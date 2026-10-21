// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tender2.dart';

part 'v1_pos_customer_accounts_uuid_repayments_request_body.g.dart';

@JsonSerializable()
class V1PosCustomerAccountsUuidRepaymentsRequestBody {
  const V1PosCustomerAccountsUuidRepaymentsRequestBody({
    required this.amount,
    required this.tender,
    this.cardType,
    this.transactionId,
    this.note,
  });

  factory V1PosCustomerAccountsUuidRepaymentsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosCustomerAccountsUuidRepaymentsRequestBodyFromJson(json);

  final num amount;
  final Tender2 tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1PosCustomerAccountsUuidRepaymentsRequestBodyToJson(this);
}
