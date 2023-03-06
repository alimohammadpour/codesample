import { 
    FETCH_GET_ATTACKS_REQUEST, 
    FETCH_GET_ATTACKS_SUCCESS, 
    FETCH_GET_ATTACKS_FAILURE 
} from '../enums/attacksTypes';

export interface Attack {
    id: string;
    sourceIP: string;
    sourcePort: number;
    destinationIP: string;
    destinationPort: number
}

export interface AttacksPagination {
    entities  : Attack[];
    totalCount: number
}

export interface AttacksSearchField {
    field: null | string;
    value: string;
}

export interface AttacksQueryParams {
    searchField: AttacksSearchField;
    page  : number;
    limit : number
}

export interface FetchGetAttacksRequestPayload {
    queryParams: AttacksQueryParams
}

export interface FetchGetAttacksSuccessPayload {
    attacks: AttacksPagination
}

export interface FetchGetAttacksFailurePayload {
    error: string
}

export type FetchGetAttacksRequest = {
    type: typeof FETCH_GET_ATTACKS_REQUEST,
    payload: FetchGetAttacksRequestPayload
}

export type FetchGetAttacksSuccess = {
    type: typeof FETCH_GET_ATTACKS_SUCCESS,
    payload: FetchGetAttacksSuccessPayload
}

export type FetchGetAttacksFailure = {
    type: typeof FETCH_GET_ATTACKS_FAILURE,
    payload: FetchGetAttacksFailurePayload
}

export type AttacksState = {
    pending: boolean;
    attacks: AttacksPagination;
    error: string | null
} 

export type AttacksActionType = FetchGetAttacksRequest | FetchGetAttacksSuccess | FetchGetAttacksFailure;
