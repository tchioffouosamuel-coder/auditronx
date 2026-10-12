import { Header } from '@/components/Header';
import { Hero } from '@/components/Hero';
import { ProblemSolution } from '@/components/ProblemSolution';
import { Features } from '@/components/Features';
import { Screenshots } from '@/components/Screenshots';
import { MobileShowcase } from '@/components/MobileShowcase';
import { Benefits } from '@/components/Benefits';
import { Trust } from '@/components/Trust';
import { Pricing } from '@/components/Pricing';
import { FinalCTA } from '@/components/FinalCTA';
import { Footer } from '@/components/Footer';
import { LegalPage } from '@/components/LegalPage';
import { LEGAL_DOCUMENTS } from '@/content/legal';

function App() {
  const pathname = window.location.pathname.replace(/\/index\.html$/, '/').replace(/\/?$/, '/');
  const legalDocument = LEGAL_DOCUMENTS.find((document) => document.path === pathname);

  return (
    <div className="min-h-screen bg-white text-ink-900">
      <Header solid={Boolean(legalDocument)} />
      <main>
        {legalDocument ? (
          <LegalPage document={legalDocument} />
        ) : (
          <>
            <Hero />
            <ProblemSolution />
            <Features />
            <Screenshots />
            <MobileShowcase />
            <Benefits />
            <Trust />
            <Pricing />
            <FinalCTA />
          </>
        )}
      </main>
      <Footer activeLegalPath={legalDocument?.path} />
    </div>
  );
}

export default App;
