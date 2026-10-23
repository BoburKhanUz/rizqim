// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voids.g.dart';

@JsonSerializable()
class Voids {
  const Voids({required this.count, required this.amount});

  factory Voids.fromJson(Map<String, Object?> json) => _$VoidsFromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$VoidsToJson(this);
}
