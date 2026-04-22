<div class="card">
    <div class="card-header"><span class="dot"></span>Kaip veikia skaitmeninis parašas</div>
    <div class="card-body">
        <div class="row">
            <div class="col">
                <div class="info-block">
                    <strong>1. SHA-256 maiša</strong><br>
                    Iš pranešimo apskaičiuojamas unikalus „pirštų atspaudas". Net vienas simbolis keičia visą maišą.
                </div>
            </div>
            <div class="col">
                <div class="info-block">
                    <strong>2. Pasirašymas</strong><br>
                    Maiša šifruojama privačiuoju RSA raktu. Rezultatas – skaitmeninis parašas (Base64).
                </div>
            </div>
            <div class="col">
                <div class="info-block">
                    <strong>3. Tikrinimas</strong><br>
                    Gavėjas iššifruoja parašą viešuoju raktu ir lygina maišas. Sutampa → galiojantis.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="dot"></span>Pranešimo pasirašymas</div>
    <div class="card-body">
        <form method="POST">
            <div class="field">
                <label for="message">Pranešimas</label>
                <textarea id="message" name="message" rows="4"
                          placeholder="Įveskite pranešimą..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">&#9654; Pasirašyti ir siųsti į Programą 2</button>
        </form>

        <?php if ($result): ?>

            <?php if (isset($result['error'])): ?>
                <div class="status status-invalid" style="margin-top:16px;">
                    ✗ <?= htmlspecialchars($result['error']) ?>
                </div>

            <?php else: ?>
                <hr class="divider">

                <div class="field">
                    <label>SHA-256 maiša</label>
                    <div class="output-box blue"><?= htmlspecialchars($result['hash']) ?></div>
                </div>

                <div class="field">
                    <label>RSA parašas (Base64)</label>
                    <div class="output-box blue" style="max-height:80px;overflow-y:auto;">
                        <?= htmlspecialchars($result['signature']) ?>
                    </div>
                </div>

                <div class="field">
                    <label>Viešasis raktas (PEM)</label>
                    <div class="output-box" style="max-height:110px;overflow-y:auto;">
                        <?= htmlspecialchars($result['public_key']) ?>
                    </div>
                </div>

                <hr class="divider">
                <div class="step-arrow">↓ Siunčiama į Programą 2 per TCP socket (port <?= PROG2_PORT ?>) ↓</div>

                <div class="field">
                    <label>Išsiųstas JSON paketas</label>
                    <div class="json-preview"><?= htmlspecialchars($result['payload_json']) ?></div>
                </div>

                <div class="field">
                    <label>Atsakymas iš Programos 2</label>
                    <div class="output-box <?= str_starts_with($result['response'], 'KLAIDA') ? 'red' : 'green' ?>">
                        <?= htmlspecialchars($result['response']) ?>
                    </div>
                </div>

                <?php if (str_starts_with($result['response'], 'KLAIDA')): ?>
                    <div class="info-block warn" style="margin-top:10px;">
                        <strong>Programa 2 neveikia.</strong> Terminale paleiskite:<br>
                        <code>php program2.php</code>
                    </div>
                <?php else: ?>
                    <div class="status status-valid">✓ Duomenys perduoti į Programą 2</div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>