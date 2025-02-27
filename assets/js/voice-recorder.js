// Voice Recorder for Chat
document.addEventListener("DOMContentLoaded", function () {
  // Check if recording elements exist on the page
  const startButton = document.getElementById("start-recording");
  const stopButton = document.getElementById("stop-recording");
  const cancelButton = document.getElementById("cancel-recording");
  const recordingContainer = document.getElementById(
    "voice-recording-container"
  );
  const voiceDataInput = document.getElementById("voice-data");
  const timerDisplay = document.getElementById("recording-timer");

  if (!startButton || !stopButton || !cancelButton) return;

  let mediaRecorder;
  let audioChunks = [];
  let timer;
  let seconds = 0;
  let minutes = 0;
  const MAX_RECORDING_TIME = 300; // 5 minutes in seconds

  // Format time for display
  function formatTime(minutes, seconds) {
    return `${minutes
      .toString()
      .padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
  }

  // Update timer display
  function updateTimer() {
    seconds++;
    if (seconds >= 60) {
      seconds = 0;
      minutes++;
    }

    timerDisplay.textContent = formatTime(minutes, seconds);

    // Check max recording time
    if (minutes * 60 + seconds >= MAX_RECORDING_TIME) {
      stopRecording();
    }
  }

  // Start recording
  startButton.addEventListener("click", function () {
    // Disable other media inputs
    disableMediaInputs();

    // Check browser support
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert("Browser Anda tidak mendukung perekaman audio.");
      return;
    }

    // Request microphone access
    navigator.mediaDevices
      .getUserMedia({ audio: true })
      .then((stream) => {
        // Show recording UI
        recordingContainer.style.display = "block";

        // Create MediaRecorder
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];

        // Collect audio chunks
        mediaRecorder.addEventListener("dataavailable", (event) => {
          audioChunks.push(event.data);
        });

        // Handle recording stop
        mediaRecorder.addEventListener("stop", () => {
          // Convert audio chunks to blob
          const audioBlob = new Blob(audioChunks, { type: "audio/webm" });

          // Create audio element for preview
          const audioElement = document.createElement("audio");
          audioElement.controls = true;
          audioElement.className = "voice-player";

          // Create audio URL
          const audioURL = URL.createObjectURL(audioBlob);
          audioElement.src = audioURL;

          // Show preview
          const previewContainer = document.querySelector(".media-preview");
          const previewElement = document.querySelector(
            ".media-preview-element"
          );

          // Clear any existing preview
          while (previewElement.firstChild) {
            previewElement.removeChild(previewElement.firstChild);
          }

          previewElement.appendChild(audioElement);
          previewContainer.style.display = "block";

          // Convert blob to base64 to send in form
          const reader = new FileReader();
          reader.readAsDataURL(audioBlob);
          reader.onloadend = function () {
            const base64data = reader.result;
            voiceDataInput.value = base64data;
          };

          // Hide recording UI
          recordingContainer.style.display = "none";

          // Stop all tracks in the stream
          stream.getTracks().forEach((track) => track.stop());
        });

        // Start recording
        mediaRecorder.start();

        // Start timer
        seconds = 0;
        minutes = 0;
        timerDisplay.textContent = formatTime(minutes, seconds);
        timer = setInterval(updateTimer, 1000);
      })
      .catch((error) => {
        console.error("Error accessing microphone:", error);
        alert("Gagal mengakses mikrofon. Pastikan Anda memberikan izin.");
      });
  });

  // Stop recording
  stopButton.addEventListener("click", stopRecording);

  function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== "inactive") {
      mediaRecorder.stop();
      clearInterval(timer);
    }
  }

  // Cancel recording
  cancelButton.addEventListener("click", function () {
    if (mediaRecorder && mediaRecorder.state !== "inactive") {
      mediaRecorder.stop();

      // Don't save the recording
      voiceDataInput.value = "";

      // Hide recording UI
      recordingContainer.style.display = "none";

      // Clear timer
      clearInterval(timer);

      // Enable media inputs again
      enableMediaInputs();
    }
  });

  // Disable other media inputs during recording
  function disableMediaInputs() {
    const photoInput = document.querySelector("input[name='photo']");
    const videoInput = document.querySelector("input[name='video']");

    if (photoInput) photoInput.disabled = true;
    if (videoInput) videoInput.disabled = true;
  }

  // Enable media inputs
  function enableMediaInputs() {
    const photoInput = document.querySelector("input[name='photo']");
    const videoInput = document.querySelector("input[name='video']");

    if (photoInput) photoInput.disabled = false;
    if (videoInput) videoInput.disabled = false;
  }

  // Disable recording button when other media is selected
  const fileInputs = document.querySelectorAll(".file-input");
  fileInputs.forEach((input) => {
    input.addEventListener("change", function () {
      if (this.files && this.files[0]) {
        startButton.disabled = true;
      } else {
        startButton.disabled = false;
      }
    });
  });

  // Re-enable recording button when media is removed
  const removeMediaBtn = document.querySelector(".remove-media-btn");
  if (removeMediaBtn) {
    removeMediaBtn.addEventListener("click", function () {
      startButton.disabled = false;
    });
  }
});
