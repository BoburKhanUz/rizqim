// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rates6.dart';

part 'v1_owner_pos_banquets_halls_hall_rates_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsHallsHallRatesRequestBody {
  const V1OwnerPosBanquetsHallsHallRatesRequestBody({required this.rates});

  factory V1OwnerPosBanquetsHallsHallRatesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsHallsHallRatesRequestBodyFromJson(json);

  final Rates6 rates;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsHallsHallRatesRequestBodyToJson(this);
}
