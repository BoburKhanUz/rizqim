// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voids5.g.dart';

@JsonSerializable()
class Voids5 {
  const Voids5({required this.count, required this.amount});

  factory Voids5.fromJson(Map<String, Object?> json) => _$Voids5FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Voids5ToJson(this);
}
