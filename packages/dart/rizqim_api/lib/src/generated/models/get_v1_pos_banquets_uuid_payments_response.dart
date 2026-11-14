// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data232.dart';

part 'get_v1_pos_banquets_uuid_payments_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsUuidPaymentsResponse {
  const GetV1PosBanquetsUuidPaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsUuidPaymentsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBanquetsUuidPaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data232 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsUuidPaymentsResponseToJson(this);
}
