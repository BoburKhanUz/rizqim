// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data145.dart';

part 'put_v1_owner_pos_banquets_payment_settings_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosBanquetsPaymentSettingsResponse {
  const PutV1OwnerPosBanquetsPaymentSettingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosBanquetsPaymentSettingsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerPosBanquetsPaymentSettingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data145 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosBanquetsPaymentSettingsResponseToJson(this);
}
