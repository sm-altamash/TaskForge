/* Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/public/js/idb.js */

(function(window) {
    'use strict';

    const DB_NAME = 'todo-app-db';
    const DB_VERSION = 1;
    const STORE_NAME = 'tasks';
    let db;

    /**
     * Initializes the IndexedDB database
     * @returns {Promise<IDBDatabase>}
     */
    function initDb() {
        return new Promise((resolve, reject) => {
            if (db) {
                return resolve(db);
            }

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = (event) => {
                console.error('IndexedDB error:', event.target.error);
                reject('IndexedDB error');
            };

            request.onsuccess = (event) => {
                db = event.target.result;
                console.log('IndexedDB opened successfully');
                resolve(db);
            };

            // This runs if the DB version changes or it's the first time
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                // Create the 'tasks' object store with 'id' as the key
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'id' });
                }
            };
        });
    }

    /**
     * Gets all tasks from the 'tasks' object store
     * @returns {Promise<Array>}
     */
    function getTasks() {
        return new Promise((resolve, reject) => {
            initDb().then(db => {
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.getAll();

                request.onsuccess = (event) => {
                    resolve(event.target.result);
                };

                request.onerror = (event) => {
                    console.error('Error getting tasks from IDB:', event.target.error);
                    reject('Error getting tasks');
                };
            });
        });
    }

    /**
     * Puts a single task into the object store (adds or updates)
     * @param {object} task
     * @returns {Promise}
     */
    function putTask(task) {
        return new Promise((resolve, reject) => {
            initDb().then(db => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.put(task);

                request.onsuccess = () => {
                    resolve();
                };

                request.onerror = (event) => {
                    console.error('Error putting task in IDB:', event.target.error);
                    reject('Error saving task');
                };
            });
        });
    }
    
    /**
     * Puts multiple tasks into the store at once (for sync)
     * @param {Array<object>} tasks
     * @returns {Promise}
     */
    function putAllTasks(tasks) {
        return new Promise((resolve, reject) => {
            initDb().then(db => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                
                // Clear the store first to sync
                store.clear().onsuccess = () => {
                    if (tasks.length === 0) {
                        return resolve();
                    }
                    
                    // Add all new tasks
                    let count = 0;
                    tasks.forEach(task => {
                        store.put(task).onsuccess = () => {
                            count++;
                            if (count === tasks.length) {
                                resolve();
                            }
                        };
                    });
                };
                
                transaction.onerror = (event) => {
                     console.error('Error putting all tasks in IDB:', event.target.error);
                    reject('Error syncing tasks');
                };
            });
        });
    }

    /**
     * Deletes a task from the object store
     * @param {number} taskId
     * @returns {Promise}
     */
    function deleteTask(taskId) {
        return new Promise((resolve, reject) => {
            initDb().then(db => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.delete(taskId);

                request.onsuccess = () => {
                    resolve();
                };

                request.onerror = (event) => {
                    console.error('Error deleting task from IDB:', event.target.error);
                    reject('Error deleting task');
                };
            });
        });
    }

    // Expose the helper functions to the global window object
    window.idb = {
        initDb,
        getTasks,
        putTask,
        putAllTasks,
        deleteTask
    };

})(window);