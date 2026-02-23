<!doctype html>
<html lang="fr">
  <? 
  $title = 'Validation Événements - UpcycleConnect'; 
  include 'include/head.php'; 
  ?>
  <body>

    <? include 'include/header.php'; ?>

    <main class="admin-layout">
      <? include 'include/sidebar.php'; ?>
      <!-- CONTENT -->
      <section class="admin-content">
        <section class="admin-section">
          <h1>Validation des événements</h1>
          <p class="muted">Consultez et gérez les demandes de création d'événements soumises par les acteurs du réseau.</p>

          <div class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Événement</th>
                  <th>Organisateur</th>
                  <th>Date & Lieu</th>
                  <th>Description</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <a href="details_event.php?id=1" class="detail-link">
                      <strong>Atelier Upcycling Bois</strong>
                    </a><br>
                    <span class="pill pill-green">Artisanat</span>
                  </td>
                  <td>Jean Menuisier</td>
                  <td>
                    15/03/2024 à 14:00<br>
                    <small>Atelier 4, Paris</small>
                  </td>
                  <td>Apprendre à fabriquer des petits meubles avec des palettes récupérées.</td>
                  <td>
                    <div class="row">
                      <button class="btn-primary">Valider</button>
                      <button class="btn-outline">Refuser</button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <a href="details_event.php?id=2" class="detail-link">
                      <strong>Conférence Zéro Déchet</strong>
                    </a><br>
                    <span class="pill pill-blue">Éducation</span>
                  </td>
                  <td>Association GreenLife</td>
                  <td>
                    22/03/2024 à 18:30<br>
                    <small>Salle Polyvalente, Lyon</small>
                  </td>
                  <td>Discussion sur les enjeux du recyclage et les gestes du quotidien.</td>
                  <td>
                    <div class="row">
                      <button class="btn-primary">Valider</button>
                      <button class="btn-outline">Refuser</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </main>

    <? include 'include/footer.php'; ?>
  </body>
</html>