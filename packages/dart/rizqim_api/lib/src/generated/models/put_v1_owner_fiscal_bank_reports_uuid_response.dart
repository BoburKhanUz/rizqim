// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data89.dart';

part 'put_v1_owner_fiscal_bank_reports_uuid_response.g.dart';

@JsonSerializable()
class PutV1OwnerFiscalBankReportsUuidResponse {
  const PutV1OwnerFiscalBankReportsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerFiscalBankReportsUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerFiscalBankReportsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data89 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerFiscalBankReportsUuidResponseToJson(this);
}
