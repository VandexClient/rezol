<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$call_id = $_GET['call_id'] ?? 0;
$type = $_GET['type'] ?? 'audio';

try {
    $db = new PDO('sqlite:messenger.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получаем информацию о звонке
    $stmt = $db->prepare("
        SELECT c.*, 
               u1.username as caller_name, u1.avatar as caller_avatar,
               u2.username as receiver_name, u2.avatar as receiver_avatar
        FROM calls c
        JOIN users u1 ON c.caller_id = u1.id
        JOIN users u2 ON c.receiver_id = u2.id
        WHERE c.id = ?
    ");
    $stmt->execute([$call_id]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$call) {
        header("Location: index.php");
        exit();
    }
    
    $is_caller = ($call['caller_id'] == $_SESSION['user_id']);
    $other_user = $is_caller ? $call['receiver_name'] : $call['caller_name'];
    $other_avatar = $is_caller ? $call['receiver_avatar'] : $call['caller_avatar'];
    $other_id = $is_caller ? $call['receiver_id'] : $call['caller_id'];
    
    // Проверяем существование аватара
    if (!file_exists('avatars/' . $other_avatar)) {
        $other_avatar = 'default.png';
    }
    
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    
} catch(PDOException $e) {
    die("Ошибка: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Звонок - Skype 2025</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .call-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .remote-avatar {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 4px solid #4a90e2;
            margin-bottom: 30px;
            object-fit: cover;
            box-shadow: 0 0 30px rgba(74, 144, 226, 0.5);
            transition: all 0.3s ease;
        }
        
        .remote-avatar.ringing {
            animation: pulse 2s infinite;
        }
        
        .remote-avatar.hidden {
            display: none;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(74, 144, 226, 0.7);
            }
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(74, 144, 226, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(74, 144, 226, 0);
            }
        }
        
        .video-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: none;
        }
        
        .video-container.active {
            display: block;
        }
        
        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #000;
        }
        
        #localVideo {
            position: absolute;
            width: 200px;
            height: 150px;
            bottom: 100px;
            right: 20px;
            border-radius: 10px;
            border: 2px solid #4a90e2;
            object-fit: cover;
            background: #2a2a2a;
            z-index: 10;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
        }
        
        #localVideo.visible {
            display: block;
        }
        
        #localVideo:hover {
            transform: scale(1.05);
            border-color: #ff4a4a;
        }
        
        #localVideo.minimized {
            width: 120px;
            height: 90px;
            bottom: 20px;
            right: 20px;
        }
        
        .call-info {
            text-align: center;
            margin-bottom: 40px;
            z-index: 20;
            position: relative;
        }
        
        .call-info h2 {
            font-size: 32px;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        
        .call-status {
            font-size: 18px;
            color: #a0a0a0;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        
        .call-timer {
            font-size: 24px;
            font-family: monospace;
            margin-top: 10px;
            color: #4a90e2;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        
        .call-controls {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            z-index: 20;
            position: relative;
        }
        
        .control-btn {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 28px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .control-btn:hover {
            transform: scale(1.1);
        }
        
        .control-btn.mic {
            background: #2c3e50;
            color: white;
        }
        
        .control-btn.mic.off {
            background: #e74c3c;
        }
        
        .control-btn.speaker {
            background: #2c3e50;
            color: white;
        }
        
        .control-btn.end {
            background: #e74c3c;
            color: white;
        }
        
        .control-btn.end:hover {
            background: #c0392b;
        }
        
        .waiting-message {
            text-align: center;
            margin: 40px 0;
            z-index: 20;
            position: relative;
        }
        
        .waiting-message i {
            font-size: 50px;
            color: #4a90e2;
            margin-bottom: 20px;
            animation: ring 2s infinite;
        }
        
        @keyframes ring {
            0% { transform: rotate(0); }
            10% { transform: rotate(15deg); }
            20% { transform: rotate(-15deg); }
            30% { transform: rotate(15deg); }
            40% { transform: rotate(-15deg); }
            100% { transform: rotate(0); }
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            z-index: 100;
            backdrop-filter: blur(5px);
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .connection-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 100;
            backdrop-filter: blur(5px);
        }
        
        .connection-status.connected {
            background: rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }
        
        .connection-status.disconnected {
            background: rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        .connection-status.connecting {
            background: rgba(241, 196, 15, 0.3);
            color: #f1c40f;
        }
        
        .connection-status.error {
            background: rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        .permission-message {
            text-align: center;
            padding: 20px;
            background: rgba(0,0,0,0.5);
            border-radius: 10px;
            margin: 20px;
        }
        
        .permission-message button {
            padding: 10px 20px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <button class="back-btn" onclick="endCall()">
        <i class="fas fa-arrow-left"></i> Завершить
    </button>
    
    <div class="connection-status connecting" id="connectionStatus">
        <i class="fas fa-sync-alt fa-spin"></i> Подключение...
    </div>
    
    <div class="call-container" id="callContainer">
        <!-- Аватар для аудио звонка -->
        <img src="avatars/<?php echo $other_avatar; ?>" alt="" class="remote-avatar <?php echo $call['status'] == 'calling' ? 'ringing' : ''; ?>" id="remoteAvatar" onerror="this.src='avatars/default.png'">
        
        <!-- Видео контейнер -->
        <div class="video-container" id="videoContainer">
            <video id="remoteVideo" autoplay playsinline></video>
            <video id="localVideo" autoplay playsinline muted></video>
        </div>
        
        <!-- Информация о звонке -->
        <div class="call-info">
            <h2 id="otherName"><?php echo htmlspecialchars($other_user); ?></h2>
            <div class="call-status" id="callStatus">
                <?php 
                if ($call['status'] == 'active') {
                    echo 'Разговор';
                } elseif ($is_caller) {
                    echo 'Звоним...';
                } else {
                    echo 'Входящий звонок...';
                }
                ?>
            </div>
            <div class="call-timer" id="callTimer">00:00</div>
        </div>
        
        <!-- Сообщение о необходимости разрешений -->
        <div class="permission-message" id="permissionMessage" style="display: none;">
            <p>Для звонка нужен доступ к микрофону</p>
            <button onclick="requestPermissions()">Разрешить доступ</button>
        </div>
        
        <!-- Элементы управления звонком -->
        <div class="call-controls" id="callControls">
            <button class="control-btn mic" id="micBtn" onclick="toggleMic()">
                <i class="fas fa-microphone"></i>
            </button>
            <button class="control-btn speaker" id="speakerBtn" onclick="toggleSpeaker()">
                <i class="fas fa-volume-up"></i>
            </button>
            <button class="control-btn end" onclick="endCall()">
                <i class="fas fa-phone-slash"></i>
            </button>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Глобальные переменные
        const callId = <?php echo $call_id; ?>;
        const isCaller = <?php echo $is_caller ? 'true' : 'false'; ?>;
        const callType = '<?php echo $type; ?>';
        const otherUserId = <?php echo $other_id; ?>;
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
        
        let callStatus = '<?php echo $call['status']; ?>';
        let timerInterval = null;
        let seconds = 0;
        let isMicOn = true;
        let isSpeakerOn = true;
        let localStream = null;
        let statusCheckInterval = null;
        let signalCheckInterval = null;
        
        // WebRTC переменные
        let peerConnection = null;
        let remoteStream = null;
        let iceCandidateQueue = [];
        let isAudioOnly = callType === 'audio';
        
        // STUN серверы Google (бесплатные)
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' },
                { urls: 'stun:stun3.l.google.com:19302' },
                { urls: 'stun:stun4.l.google.com:19302' }
            ]
        };
        
        // Проверка поддержки медиа
        function checkMediaSupport() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                document.getElementById('connectionStatus').className = 'connection-status error';
                document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-exclamation-triangle"></i> HTTPS требуется';
                document.getElementById('permissionMessage').style.display = 'block';
                return false;
            }
            return true;
        }
        
        // ===== ИНИЦИАЛИЗАЦИЯ =====
        
        async function initializeCall() {
            if (!checkMediaSupport()) {
                return;
            }
            
            try {
                // Запрашиваем доступ к медиа
                const constraints = {
                    audio: true,
                    video: !isAudioOnly
                };
                
                console.log('Запрашиваем доступ к медиа...');
                localStream = await navigator.mediaDevices.getUserMedia(constraints);
                console.log('Получен локальный поток');
                
                // Показываем локальное видео если есть видео
                if (!isAudioOnly) {
                    document.getElementById('localVideo').srcObject = localStream;
                    document.getElementById('localVideo').classList.add('visible');
                    document.getElementById('videoContainer').classList.add('active');
                    document.getElementById('remoteAvatar').classList.add('hidden');
                }
                
                // Создаем peer connection
                createPeerConnection();
                
                // Добавляем локальные треки
                localStream.getTracks().forEach(track => {
                    peerConnection.addTrack(track, localStream);
                });
                
                // Если мы звонящий, создаем offer
                if (isCaller && callStatus === 'calling') {
                    setTimeout(() => createOffer(), 1000);
                }
                
            } catch (error) {
                console.error('Ошибка доступа к медиа:', error);
                
                if (error.name === 'NotAllowedError') {
                    document.getElementById('connectionStatus').className = 'connection-status error';
                    document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-ban"></i> Доступ запрещен';
                    document.getElementById('permissionMessage').style.display = 'block';
                } else if (error.name === 'NotFoundError') {
                    document.getElementById('connectionStatus').className = 'connection-status error';
                    document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-microphone-slash"></i> Микрофон не найден';
                } else {
                    document.getElementById('connectionStatus').className = 'connection-status error';
                    document.getElementById('connectionStatus').innerHTML = '<i class="fas fa-exclamation-circle"></i> Ошибка устройств';
                }
            }
        }
        
        function createPeerConnection() {
            peerConnection = new RTCPeerConnection(configuration);
            
            // Обработка ICE кандидатов
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal('ice_candidate', {
                        candidate: event.candidate,
                        to: otherUserId
                    });
                }
            };
            
            // Обработка состояния соединения
            peerConnection.onconnectionstatechange = () => {
                console.log('Состояние соединения:', peerConnection.connectionState);
                
                const statusEl = document.getElementById('connectionStatus');
                if (peerConnection.connectionState === 'connected') {
                    statusEl.className = 'connection-status connected';
                    statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Подключено';
                } else if (peerConnection.connectionState === 'disconnected' || peerConnection.connectionState === 'failed') {
                    statusEl.className = 'connection-status disconnected';
                    statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Отключено';
                } else if (peerConnection.connectionState === 'connecting') {
                    statusEl.className = 'connection-status connecting';
                    statusEl.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Подключение...';
                }
            };
            
            // Получение удаленного потока
            peerConnection.ontrack = (event) => {
                console.log('Получен удаленный поток');
                
                if (!remoteStream) {
                    remoteStream = event.streams[0];
                    
                    if (!isAudioOnly) {
                        // Видеозвонок
                        document.getElementById('remoteVideo').srcObject = remoteStream;
                        document.getElementById('videoContainer').classList.add('active');
                        document.getElementById('remoteAvatar').classList.add('hidden');
                    } else {
                        // Аудиозвонок - создаем скрытый аудио элемент
                        const audio = new Audio();
                        audio.srcObject = remoteStream;
                        audio.play().catch(e => console.log('Автовоспроизведение заблокировано'));
                    }
                }
            };
            
            // Отправляем накопленные ICE кандидаты
            if (iceCandidateQueue.length > 0) {
                iceCandidateQueue.forEach(candidate => {
                    peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                });
                iceCandidateQueue = [];
            }
        }
        
        async function createOffer() {
            try {
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                
                sendSignal('offer', {
                    offer: peerConnection.localDescription,
                    to: otherUserId
                });
                
                console.log('Offer отправлен');
            } catch (error) {
                console.error('Ошибка создания offer:', error);
            }
        }
        
        // ===== ОТПРАВКА СИГНАЛОВ =====
        
        function sendSignal(type, data) {
            $.ajax({
                url: 'call_signal.php',
                method: 'POST',
                data: {
                    type: type,
                    call_id: callId,
                    data: JSON.stringify(data)
                },
                success: function(response) {
                    console.log('Сигнал отправлен:', type);
                },
                error: function(error) {
                    console.error('Ошибка отправки сигнала:', error);
                }
            });
        }
        
        // ===== ПОЛУЧЕНИЕ СИГНАЛОВ =====
        
        function checkSignals() {
            $.ajax({
                url: 'call_signal.php?type=get&call_id=' + callId + '&t=' + Date.now(),
                method: 'GET',
                success: function(signals) {
                    if (signals && signals.length > 0) {
                        signals.forEach(signal => {
                            processSignal(signal);
                        });
                    }
                },
                error: function(error) {
                    console.error('Ошибка получения сигналов:', error);
                }
            });
        }
        
        function processSignal(signal) {
            console.log('Получен сигнал:', signal.type);
            
            try {
                const data = JSON.parse(signal.data);
                
                switch(signal.type) {
                    case 'offer':
                        handleOffer(data.offer);
                        break;
                        
                    case 'answer':
                        handleAnswer(data.answer);
                        break;
                        
                    case 'ice_candidate':
                        handleIceCandidate(data.candidate);
                        break;
                }
            } catch (e) {
                console.error('Ошибка обработки сигнала:', e);
            }
        }
        
        async function handleOffer(offer) {
            try {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                
                sendSignal('answer', {
                    answer: peerConnection.localDescription,
                    to: otherUserId
                });
                
                console.log('Answer отправлен');
            } catch (error) {
                console.error('Ошибка обработки offer:', error);
            }
        }
        
        async function handleAnswer(answer) {
            try {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
                console.log('Answer обработан');
            } catch (error) {
                console.error('Ошибка обработки answer:', error);
            }
        }
        
        async function handleIceCandidate(candidate) {
            try {
                if (peerConnection) {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                } else {
                    iceCandidateQueue.push(candidate);
                }
            } catch (error) {
                console.error('Ошибка добавления ICE кандидата:', error);
            }
        }
        
        // ===== ПРОВЕРКА СТАТУСА ЗВОНКА =====
        
        function checkCallStatus() {
            $.ajax({
                url: 'check_call_status.php?call_id=' + callId + '&t=' + Date.now(),
                method: 'GET',
                success: function(data) {
                    console.log('Статус звонка:', data);
                    
                    if (data.status === 'active' && callStatus !== 'active') {
                        // ЗВОНОК ПРИНЯТ!
                        callStatus = 'active';
                        document.getElementById('callStatus').textContent = 'Разговор';
                        document.getElementById('remoteAvatar').classList.remove('ringing');
                        startTimer();
                        
                    } else if (data.status === 'rejected' && callStatus !== 'rejected') {
                        document.getElementById('callStatus').textContent = 'Звонок отклонен';
                        setTimeout(() => window.location.href = 'index.php', 2000);
                        
                    } else if (data.status === 'ended' && callStatus !== 'ended') {
                        document.getElementById('callStatus').textContent = 'Звонок завершен';
                        setTimeout(() => window.location.href = 'index.php', 2000);
                    }
                },
                error: function(error) {
                    console.error('Ошибка проверки статуса:', error);
                }
            });
        }
        
        // ===== УПРАВЛЕНИЕ ЗВОНКОМ =====
        
        function endCall() {
            if (statusCheckInterval) clearInterval(statusCheckInterval);
            if (signalCheckInterval) clearInterval(signalCheckInterval);
            
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            
            if (peerConnection) {
                peerConnection.close();
            }
            
            $.ajax({
                url: 'end_call.php',
                method: 'POST',
                data: {
                    call_id: callId,
                    duration: seconds
                },
                complete: function() {
                    window.location.href = 'index.php';
                }
            });
        }
        
        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            
            timerInterval = setInterval(() => {
                seconds++;
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                document.getElementById('callTimer').textContent = 
                    `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }, 1000);
        }
        
        function toggleMic() {
            isMicOn = !isMicOn;
            const micBtn = document.getElementById('micBtn');
            
            if (localStream) {
                localStream.getAudioTracks().forEach(track => {
                    track.enabled = isMicOn;
                });
            }
            
            if (isMicOn) {
                micBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                micBtn.classList.remove('off');
            } else {
                micBtn.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                micBtn.classList.add('off');
            }
        }
        
        function toggleSpeaker() {
            isSpeakerOn = !isSpeakerOn;
            const speakerBtn = document.getElementById('speakerBtn');
            
            if (isSpeakerOn) {
                speakerBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
            } else {
                speakerBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
            }
        }
        
        function toggleLocalVideo() {
            const localVideo = document.getElementById('localVideo');
            localVideo.classList.toggle('minimized');
        }
        
        function requestPermissions() {
            initializeCall();
            document.getElementById('permissionMessage').style.display = 'none';
        }
        
        // ===== ЗАПУСК =====
        
        document.addEventListener('DOMContentLoaded', function() {
            // Проверяем поддержку медиа
            checkMediaSupport();
            
            // Запускаем инициализацию
            setTimeout(initializeCall, 500);
            
            // Если звонок уже активен, запускаем таймер
            if (callStatus === 'active') {
                startTimer();
            }
            
            // Проверяем статус каждую секунду
            statusCheckInterval = setInterval(checkCallStatus, 1000);
            
            // Проверяем сигналы каждые 2 секунды
            signalCheckInterval = setInterval(checkSignals, 2000);
            
            // Клик по локальному видео для изменения размера
            document.getElementById('localVideo').addEventListener('click', toggleLocalVideo);
        });
        
        // При закрытии страницы
        window.addEventListener('beforeunload', function() {
            if (statusCheckInterval) clearInterval(statusCheckInterval);
            if (signalCheckInterval) clearInterval(signalCheckInterval);
            
            if (callStatus === 'active' || callStatus === 'calling') {
                const data = new FormData();
                data.append('call_id', callId);
                data.append('duration', seconds);
                navigator.sendBeacon('end_call.php', data);
            }
        });
    </script>
</body>
</html>