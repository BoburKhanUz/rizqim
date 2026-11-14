// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'slots10.g.dart';

@JsonSerializable()
class Slots10 {
  const Slots10({
    required this.at,
    required this.time,
    required this.businessDate,
  });

  factory Slots10.fromJson(Map<String, Object?> json) =>
      _$Slots10FromJson(json);

  final String at;
  final String time;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Slots10ToJson(this);
}
