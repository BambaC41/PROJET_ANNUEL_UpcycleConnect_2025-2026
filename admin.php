<!doctype html>
<html lang="fr">
  <? 
  $title = 'Administration - UpcycleConnect'; 
  include 'include/head.php'; 
  ?>
  <body>
  <body class="bg-gray-100 flex h-screen overflow-hidden">

    <? include 'include/header.php'; ?>
    <div class="admin-layout w-full">
      <? include 'include/sidebar.php'; ?>

    <main class="admin-layout">
      <? include 'include/sidebar.php'; ?>
      <!-- CONTENT -->
      <section class="admin-content">
      <main class="admin-content">
        <header class="flex justify-between items-center mb-10">
            <h2 class="text-3xl font-bold text-gray-800">Dashboard Global</h2>
            <div class="text-sm text-gray-500">Session Admin active</div>
        </header>

        <!-- DASHBOARD -->
        <section id="dashboard" class="admin-section">
          <h1>Dashboard Global</h1>

        <section id="dashboard" class="mb-10">
          <div class="admin-kpis">
            <div class="admin-card">
              <h3>Utilisateurs</h3>
              <p>1 284</p>
              <p class="text-gray-500 text-sm">Utilisateurs</p>
              <p class="text-3xl font-bold">1 284</p>
            </div>

            <div class="admin-card">
              <h3>Professionnels</h3>
              <p>146</p>
              <p class="text-gray-500 text-sm">Professionnels</p>
              <p class="text-3xl font-bold">146</p>
            </div>

            <div class="admin-card">
              <h3>Événements actifs</h3>
              <p>23</p>
              <p class="text-gray-500 text-sm">Événements actifs</p>
              <p class="text-3xl font-bold">23</p>
            </div>

            <div class="admin-card">
              <h3>Revenus mensuels</h3>
              <p>8 420€</p>
            </div>
          </div>
        </section>

        <!-- ACTEURS -->
        <section id="actors" class="admin-section">
          <h2>Gestion des acteurs</h2>
          <p>Particuliers • Professionnels • Salariés</p>
          <button class="btn-primary">Voir détails acteurs</button>
        </section>

        <!-- VALIDATION EVENEMENTS -->
        <section id="events" class="admin-section">
          <h2>Validation des événements</h2>
          <table class="admin-table">
            <tr>
              <th>Titre</th>
              <th>Date</th>
              <th>Statut</th>
              <th>Action</th>
            </tr>
            <tr>
              <td><a href="details_event.php?id=1" class="detail-link">Atelier Bois</a></td>
              <td>12/03</td>
              <td>En attente</td>
              <td>
                <button class="btn-primary">Valider</button>
                <button class="btn-outline">Refuser</button>
              </td>
            </tr>
          </table>
        </section>

        <!-- CATALOGUE -->
        <section id="catalog" class="admin-section">
          <h2>Gestion du catalogue des offres</h2>
          <button class="btn-primary">Ajouter prestation</button>
          <button class="btn-outline">Gérer catégories</button>
        </section>

        <!-- NOTIFICATIONS -->
        <section id="notifications" class="admin-section">
          <h2>Notifications</h2>
          <button class="btn-primary">Envoyer notification</button>
          <p>
            Historique des notifications envoyées aux particuliers et
            professionnels
          </p>
        </section>

        <!-- CONTENEURS -->
        <section id="containers" class="admin-section">
          <h2>Gestion des conteneurs / box</h2>
          <div class="admin-card">
            <h3>UC-PAR-12</h3>
            <p>Statut : Opérationnel</p>
            <button class="btn-outline">Voir détails</button>
          </div>
        </section>

        <!-- DOCUMENTS -->
        <section id="documents" class="admin-section">
          <h2>Documents & Codes</h2>
          <ul>
            <li>Contrat Pro - PDF</li>
            <li>Facture Mars - PDF</li>
            <li>Code retrait : 7H2K-19Q</li>
          </ul>
        </section>

        <!-- FINANCES -->
        <section id="finance" class="admin-section">
          <h2>Gestion financière</h2>

          <div class="admin-finance">
            <div>
              <h3>Revenus</h3>
              <p>8 420€</p>
            </div>
            <div>
              <h3>Charges</h3>
              <p>1 660€</p>
            </div>
          </div>
        </section>

        <!-- PLANNING -->
        <section id="planning" class="admin-section">
          <h2>Accès aux plannings</h2>
          <button class="btn-outline">Planning salariés</button>
          <button class="btn-outline">Planning événements</button>
        </section>
      </section>
    </main>
  </body>
  <? include 'include/footer.php'; ?>
</html>
