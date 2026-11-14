// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data222.dart';

part 'get_v1_pos_banquets_osh_calculator_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsOshCalculatorResponse {
  const GetV1PosBanquetsOshCalculatorResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsOshCalculatorResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBanquetsOshCalculatorResponseFromJson(json);

  final bool success;
  final String message;
  final Data222 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsOshCalculatorResponseToJson(this);
}
