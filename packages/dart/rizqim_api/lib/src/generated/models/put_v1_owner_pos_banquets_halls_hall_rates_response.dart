// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data137.dart';

part 'put_v1_owner_pos_banquets_halls_hall_rates_response.g.dart';

@JsonSerializable()
class PutV1OwnerPosBanquetsHallsHallRatesResponse {
  const PutV1OwnerPosBanquetsHallsHallRatesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPosBanquetsHallsHallRatesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerPosBanquetsHallsHallRatesResponseFromJson(json);

  final bool success;
  final String message;
  final Data137 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerPosBanquetsHallsHallRatesResponseToJson(this);
}
