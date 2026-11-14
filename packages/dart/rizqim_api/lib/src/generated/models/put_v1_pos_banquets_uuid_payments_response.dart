// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data233.dart';

part 'put_v1_pos_banquets_uuid_payments_response.g.dart';

@JsonSerializable()
class PutV1PosBanquetsUuidPaymentsResponse {
  const PutV1PosBanquetsUuidPaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1PosBanquetsUuidPaymentsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1PosBanquetsUuidPaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data233 data;

  Map<String, Object?> toJson() =>
      _$PutV1PosBanquetsUuidPaymentsResponseToJson(this);
}
