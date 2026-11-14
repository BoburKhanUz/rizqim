// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'guests4.g.dart';

@JsonSerializable()
class Guests4 {
  const Guests4({
    required this.guaranteed,
    required this.minimum,
    required this.actual,
    required this.billed,
  });

  factory Guests4.fromJson(Map<String, Object?> json) =>
      _$Guests4FromJson(json);

  final int guaranteed;
  final int minimum;
  final int? actual;
  final int billed;

  Map<String, Object?> toJson() => _$Guests4ToJson(this);
}
