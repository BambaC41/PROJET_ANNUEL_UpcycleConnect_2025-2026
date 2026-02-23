<!doctype html>
<html lang="fr">
  <? 
  $title = 'Gestion des Utilisateurs - UpcycleConnect'; 
  include 'include/head.php'; 
  ?>
  <body>

    <? include 'include/header.php'; ?>

    <main class="admin-layout">
      <? include 'include/sidebar.php'; ?>

      <section class="admin-content">
        <section class="admin-section">
          <div class="panel-head">
            <div>
              <h1>Gestion des utilisateurs</h1>
              <p class="muted">Administrez les comptes des particuliers et des professionnels du réseau.</p>
            </div>
            <div class="dash-actions">
              <button class="btn-primary">Ajouter un utilisateur</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Nom</th>
                  <th>Email</th>
                  <th>Rôle</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <a href="details_user.php?id=1" class="detail-link">
                      <strong>Marie Dupont</strong>
                    </a>
                  </td>
                  <td>marie@mail.com</td>
                  <td>Particulier</td>
                  <td><span class="pill pill-green">Actif</span></td>
                  <td>
                    <button class="btn-outline">Modifier</button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <a href="details_user.php?id=2" class="detail-link">
                      <strong>Marc Durand</strong>
                    </a>
                  </td>
                  <td>marc.pro@gmail.com</td>
                  <td>Professionnel</td>
                  <td><span class="pill pill-green">Actif</span></td>
                  <td>
                    <button class="btn-outline">Modifier</button>
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
