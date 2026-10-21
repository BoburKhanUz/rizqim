// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_finance_response.dart';
import '../models/post_v1_owner_finance_top_up_response.dart';
import '../models/post_v1_owner_payouts_response.dart';
import '../models/v1_owner_finance_top_up_request_body.dart';
import '../models/v1_owner_payouts_request_body.dart';

part 'owner_finance_api.g.dart';

@RestApi()
abstract class OwnerFinanceApi {
  factory OwnerFinanceApi(Dio dio, {String? baseUrl}) = _OwnerFinanceApi;

  /// Get store balance details and recent financial transactions.
  ///
  /// finance.view (the owner; an owner may grant it to managers), never from a terminal PIN session. The balances are.
  /// numbers (as since v1); every other amount is a two-decimal string. Card numbers are masked, in notes too.
  @GET('/v1/owner/finance')
  Future<GetV1OwnerFinanceResponse> ownerFinanceIndex();

  /// Initiate a store balance top-up payment
  @POST('/v1/owner/finance/top-up')
  Future<PostV1OwnerFinanceTopUpResponse> ownerFinanceInitiateTopup({
    @Body() required V1OwnerFinanceTopUpRequestBody body,
  });

  /// Create a new payout request.
  ///
  /// Answers the request in the shape of GET /owner/payouts (card masked), never the stored card number.
  @POST('/v1/owner/payouts')
  Future<PostV1OwnerPayoutsResponse> ownerFinanceRequestPayout({
    @Body() required V1OwnerPayoutsRequestBody body,
  });
}
