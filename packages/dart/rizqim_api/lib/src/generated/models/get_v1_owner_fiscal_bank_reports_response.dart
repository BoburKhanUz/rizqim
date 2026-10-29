// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data73.dart';

part 'get_v1_owner_fiscal_bank_reports_response.g.dart';

@JsonSerializable()
class GetV1OwnerFiscalBankReportsResponse {
  const GetV1OwnerFiscalBankReportsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFiscalBankReportsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerFiscalBankReportsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data73> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerFiscalBankReportsResponseToJson(this);
}
