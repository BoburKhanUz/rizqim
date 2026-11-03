// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'slots5.g.dart';

@JsonSerializable()
class Slots5 {
  const Slots5({
    required this.at,
    required this.time,
    required this.businessDate,
  });

  factory Slots5.fromJson(Map<String, Object?> json) => _$Slots5FromJson(json);

  final String at;
  final String time;
  @JsonKey(name: 'business_date')
  final String businessDate;

  Map<String, Object?> toJson() => _$Slots5ToJson(this);
}
