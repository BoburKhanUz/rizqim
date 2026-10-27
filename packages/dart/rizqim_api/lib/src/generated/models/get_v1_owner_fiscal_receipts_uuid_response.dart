// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data66.dart';

part 'get_v1_owner_fiscal_receipts_uuid_response.g.dart';

@JsonSerializable()
class GetV1OwnerFiscalReceiptsUuidResponse {
  const GetV1OwnerFiscalReceiptsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFiscalReceiptsUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerFiscalReceiptsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data66 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerFiscalReceiptsUuidResponseToJson(this);
}
