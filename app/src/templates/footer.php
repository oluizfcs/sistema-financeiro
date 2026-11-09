        </div>
    </div>
<div id="generatingPdfModal" class="modal" style="display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
    <div class="modal-content" style="background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:40%; border-radius: 8px;">
        <div class="report-progress">
            <div class="report-progress-head">
                <span>Gerando PDF</span><span class="loader"></span>
            </div>
            <p id="reportStage" class="report-stage">Consultando dados…</p>
            <div class="progress-track">
                <div class="progress-fill"></div>
            </div>
            <p id="reportElapsed" class="report-elapsed"></p>
        </div>
    </div>
</div>
<style>
    .modal-content {
        display: flex;
        justify-content: space-between;
    }

    .report-progress {
        width: 100%;
    }

    .report-progress-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
    }

    .report-stage {
        margin: 12px 0 6px;
        color: #333;
        font-size: 14px;
        min-height: 18px;
    }

    .report-elapsed {
        margin: 6px 0 0;
        color: #888;
        font-size: 12px;
        text-align: right;
    }

    .progress-track {
        width: 100%;
        height: 10px;
        background: #e0e4ee;
        border-radius: 5px;
        overflow: hidden;
    }

    .progress-fill {
        width: 35%;
        height: 100%;
        border-radius: 5px;
        background: linear-gradient(90deg, #2c4a88, #4d7ad8, #2c4a88);
        animation: marquee 1.4s ease-in-out infinite;
    }

    @keyframes marquee {
        0% {
            transform: translateX(-110%);
        }

        55% {
            transform: translateX(290%);
        }

        100% {
            transform: translateX(290%);
        }
    }

    .loader {
        width: 24px;
        height: 24px;
        border: 5px solid;
        border-color: hsl(214, 77%, 14%) transparent;
        border-radius: 50%;
        display: inline-block;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .report-dropdown {
        position: relative;
        display: inline-block;
    }

    .report-menu {
        position: absolute;
        left: 0;
        top: calc(100% + 2px);
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 100;
        min-width: 240px;
        padding: 6px 0;
    }

    .report-menu-orientation {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        padding: 0 12px 8px 12px;
        margin-bottom: 6px;
        border-bottom: 1px solid #eee;
        color: #333;
        font-size: 0.9em;
    }

    .report-menu-orientation span {
        font-weight: 600;
    }

    .report-menu-orientation label {
        display: flex;
        align-items: center;
        gap: 3px;
        cursor: pointer;
    }

    .report-menu-field {
        padding: 4px 12px;
        color: #333;
        font-size: 0.9em;
        display: grid;
        gap: 2px;
    }

    .report-menu button {
        display: block;
        width: 100%;
        padding: 8px 12px;
        border: none;
        background: transparent;
        color: #333;
        text-align: left;
        font-size: 0.95em;
        cursor: pointer;
    }

    .report-menu button:hover {
        background-color: #e8ebf5;
        color: #1a1a1a;
    }
</style>
<script>
    document.addEventListener('click', function(e) {
        const dropdown = document.querySelector('.report-dropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            const menu = document.getElementById('report-menu');
            if (menu) menu.style.display = 'none';
        }
    });
</script>
<script src="<?= $_ENV['BASE_URL'] ?>/js/generateReport.js"></script>
<script src="<?= $_ENV['BASE_URL'] ?>/js/charcounter.js"></script>
<script src="<?= $_ENV['BASE_URL'] ?>/js/preventDoubleSubmit.js"></script>
<script>
$(document).ready(function () {
    $('.select2').select2({
        language: {
            noResults: function () {
                return "Nenhuma opção encontrada."
            }
        }
    });
    
    $('.money').maskMoney({
        prefix: 'R$ '
    }).trigger('mask.maskMoney');

    document.getElementById("content").focus();
});
</script>
<script>
    setTimeout(function() {
        const msgs = document.querySelectorAll('.msg');
        msgs.forEach(function(msg) {
            msg.style.display = 'none';
        });
    }, 4000);
</script>
</body>
</html>
