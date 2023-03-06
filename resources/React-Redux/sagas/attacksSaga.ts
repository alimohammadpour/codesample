import { all, takeLatest, call, put } from 'redux-saga/effects';
import { FETCH_GET_ATTACKS_REQUEST } from '../actions/enums/attacksTypes';
import { FetchGetAttacksRequest, AttacksQueryParams, AttacksPagination } from '../actions/types/attacksTypes';
import { api } from '../api';
import { AxiosResponse } from 'axios';
import { fetchGetAttacksSuccess, fetchGetAttacksFailure } from '../actions/attacksAction';
import { ErrorHandler } from '../classes/ErrorHandler';

export const getAttacks = (queryParams: AttacksQueryParams) => api.get('attack', { params: queryParams });

export function* fetchGetAttacksRequestSaga({payload}: FetchGetAttacksRequest) {
    try {
        const response: AxiosResponse<AttacksPagination> = yield call(getAttacks, payload.queryParams);

        yield put(fetchGetAttacksSuccess({ attacks: response.data }));
    } catch (error: unknown) {
        const errorMessage = ErrorHandler.getMessage(error);
        yield put(fetchGetAttacksFailure({ error: errorMessage }))
    }
}

export function* getAttacksSaga() {
    yield all([
        takeLatest<FetchGetAttacksRequest>(FETCH_GET_ATTACKS_REQUEST, fetchGetAttacksRequestSaga)
    ])
}
