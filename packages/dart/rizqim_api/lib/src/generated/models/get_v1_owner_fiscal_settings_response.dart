// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data69.dart';

part 'get_v1_owner_fiscal_settings_response.g.dart';

@JsonSerializable()
class GetV1OwnerFiscalSettingsResponse {
  const GetV1OwnerFiscalSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFiscalSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerFiscalSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data69 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerFiscalSettingsResponseToJson(this);
}
