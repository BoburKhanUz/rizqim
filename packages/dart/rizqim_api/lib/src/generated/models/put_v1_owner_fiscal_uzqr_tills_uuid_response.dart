// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data103.dart';

part 'put_v1_owner_fiscal_uzqr_tills_uuid_response.g.dart';

@JsonSerializable()
class PutV1OwnerFiscalUzqrTillsUuidResponse {
  const PutV1OwnerFiscalUzqrTillsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerFiscalUzqrTillsUuidResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerFiscalUzqrTillsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data103 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerFiscalUzqrTillsUuidResponseToJson(this);
}
