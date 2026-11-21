// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'continued4.g.dart';

@JsonSerializable()
class Continued4 {
  const Continued4({required this.count, required this.amount});

  factory Continued4.fromJson(Map<String, Object?> json) =>
      _$Continued4FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Continued4ToJson(this);
}
