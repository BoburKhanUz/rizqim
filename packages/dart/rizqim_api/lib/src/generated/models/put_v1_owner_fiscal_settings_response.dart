// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data82.dart';

part 'put_v1_owner_fiscal_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerFiscalSettingsResponse {
  const PutV1OwnerFiscalSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerFiscalSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerFiscalSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data82 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerFiscalSettingsResponseToJson(this);
}
