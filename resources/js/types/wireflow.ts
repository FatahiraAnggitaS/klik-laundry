import type { ActiveRole, RoleOption } from '@/types/dashboard';

export interface WireflowBranch {
    label: string;
    result: string;
}

export interface WireflowStep {
    id: string;
    number: string;
    title: string;
    actor: string;
    trigger: string;
    systemOutcome: string;
    status: string;
    privacy: string;
    branches: WireflowBranch[];
}

export interface Wireflow {
    title: string;
    summary: string;
    outcome: string;
    steps: WireflowStep[];
}

export interface RiskNotice {
    title: string;
    description: string;
    severity: 'blocker';
}

export interface WireflowPageProps {
    activeRole: ActiveRole;
    roles: RoleOption[];
    wireflow: Wireflow;
    activeStep: WireflowStep;
    activeStepIndex: number;
    previousStepId: string | null;
    nextStepId: string | null;
    riskNotices: RiskNotice[];
}
