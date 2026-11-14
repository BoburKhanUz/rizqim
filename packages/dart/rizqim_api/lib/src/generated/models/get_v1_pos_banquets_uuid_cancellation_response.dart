// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data226.dart';

part 'get_v1_pos_banquets_uuid_cancellation_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsUuidCancellationResponse {
  const GetV1PosBanquetsUuidCancellationResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsUuidCancellationResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBanquetsUuidCancellationResponseFromJson(json);

  final bool success;
  final String message;
  final Data226 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsUuidCancellationResponseToJson(this);
}
