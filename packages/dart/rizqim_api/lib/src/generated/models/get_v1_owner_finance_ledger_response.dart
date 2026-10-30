// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data84.dart';

part 'get_v1_owner_finance_ledger_response.g.dart';

@JsonSerializable()
class GetV1OwnerFinanceLedgerResponse {
  const GetV1OwnerFinanceLedgerResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFinanceLedgerResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerFinanceLedgerResponseFromJson(json);

  final bool success;
  final String message;
  final Data84 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerFinanceLedgerResponseToJson(this);
}
