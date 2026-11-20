// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'offline28.g.dart';

@JsonSerializable()
class Offline28 {
  const Offline28({required this.count, required this.amount});

  factory Offline28.fromJson(Map<String, Object?> json) =>
      _$Offline28FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Offline28ToJson(this);
}
