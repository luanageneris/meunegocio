const n=()=>({getApiUrl:(o="")=>typeof window>"u"?`http://localhost:8000${o}`:window.location.hostname==="localhost"&&window.location.port==="5173"?`http://localhost:8000${o}`:o});export{n as u};
