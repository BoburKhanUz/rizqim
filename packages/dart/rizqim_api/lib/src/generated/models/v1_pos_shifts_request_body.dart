// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_shifts_request_body.g.dart';

@JsonSerializable()
class V1PosShiftsRequestBody {
  const V1PosShiftsRequestBody({required this.openingCash, this.note});

  factory V1PosShiftsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosShiftsRequestBodyFromJson(json);

  @JsonKey(name: 'opening_cash')
  final num openingCash;
  final String? note;

  Map<String, Object?> toJson() => _$V1PosShiftsRequestBodyToJson(this);
}
