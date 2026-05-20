/**
 * IMEI Barcode Scanner using html5-qrcode
 */
let html5QrCode = null;
let isScanning = false;

document.addEventListener('DOMContentLoaded', function() {
    const startBtn = document.getElementById('btn-start-scan');
    if (startBtn) {
        startBtn.addEventListener('click', toggleScanner);
    }
});

function toggleScanner() {
    if (isScanning) {
        stopScanner();
    } else {
        startScanner();
    }
}

function startScanner() {
    const container = document.getElementById('scanner-container');
    const btn = document.getElementById('btn-start-scan');
    
    container.innerHTML = '<div id="reader" style="width:100%;"></div>';
    
    html5QrCode = new Html5Qrcode("reader");
    
    html5QrCode.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 300, height: 100 },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.QR_CODE
            ]
        },
        onScanSuccess,
        onScanFailure
    ).then(() => {
        isScanning = true;
        btn.innerHTML = '<i class="fas fa-stop"></i> Stop Scanner';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-danger');
    }).catch(err => {
        container.innerHTML = `<div style="text-align:center;color:var(--danger);padding:40px;">
            <i class="fas fa-exclamation-triangle" style="font-size:2rem;margin-bottom:12px;display:block;"></i>
            <p>Camera access denied or not available</p>
            <p style="font-size:0.75rem;margin-top:8px;">${err}</p>
        </div>`;
    });
}

function stopScanner() {
    const btn = document.getElementById('btn-start-scan');
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            isScanning = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Start Scanner';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-primary');
            
            document.getElementById('scanner-container').innerHTML = `
                <div style="text-align:center;color:var(--text-muted);padding:40px;">
                    <i class="fas fa-barcode" style="font-size:3rem;margin-bottom:16px;display:block;"></i>
                    <p>Scanner stopped. Click "Start Scanner" to scan again.</p>
                </div>`;
        });
    }
}

function onScanSuccess(decodedText) {
    // Clean the scanned text - keep only digits
    const cleaned = decodedText.replace(/\D/g, '');
    
    // Validate IMEI (15 digits)
    if (cleaned.length === 15 && /^\d{15}$/.test(cleaned)) {
        // Valid IMEI found
        stopScanner();
        
        // Fill the IMEI input
        const imeiInput = document.getElementById('imei-input');
        if (imeiInput) {
            imeiInput.value = cleaned;
            imeiInput.dispatchEvent(new Event('input'));
        }
        
        // Show result
        const resultDiv = document.getElementById('scan-result');
        const scannedImei = document.getElementById('scanned-imei');
        if (resultDiv && scannedImei) {
            scannedImei.textContent = cleaned;
            resultDiv.style.display = 'block';
        }
        
        if (typeof showToast === 'function') {
            showToast('IMEI scanned: ' + cleaned, 'success');
        }
    }
}

function onScanFailure(error) {
    // Silently ignore - scanning in progress
}
