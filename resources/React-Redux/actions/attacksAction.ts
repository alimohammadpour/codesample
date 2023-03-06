import { 
    FETCH_GET_ATTACKS_REQUEST, 
    FETCH_GET_ATTACKS_SUCCESS, 
    FETCH_GET_ATTACKS_FAILURE 
} from './enums/attacksTypes';
import { 
    FetchGetAttacksRequestPayload, 
    FetchGetAttacksSuccessPayload,
    FetchGetAttacksFailurePayload,
    FetchGetAttacksRequest,  
    FetchGetAttacksSuccess,  
    FetchGetAttacksFailure 
} from './types/attacksTypes';

export const fetchGetAttacksRequest = (payload: FetchGetAttacksRequestPayload): FetchGetAttacksRequest => ({
    type: FETCH_GET_ATTACKS_REQUEST,
    payload
});

export const fetchGetAttacksSuccess = (payload: FetchGetAttacksSuccessPayload): FetchGetAttacksSuccess => ({
    type: FETCH_GET_ATTACKS_SUCCESS,
    payload
});

export const fetchGetAttacksFailure = (payload: FetchGetAttacksFailurePayload): FetchGetAttacksFailure => ({
    type: FETCH_GET_ATTACKS_FAILURE,
    payload
});
