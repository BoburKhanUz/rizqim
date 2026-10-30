// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data93.dart';

part 'get_v1_owner_fiscal_receipts_response.g.dart';

@JsonSerializable()
class GetV1OwnerFiscalReceiptsResponse {
  const GetV1OwnerFiscalReceiptsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFiscalReceiptsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerFiscalReceiptsResponseFromJson(json);

  final bool success;
  final String message;
  final Data93 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerFiscalReceiptsResponseToJson(this);
}
