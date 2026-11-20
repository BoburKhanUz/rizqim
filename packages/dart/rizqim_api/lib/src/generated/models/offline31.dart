// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'offline31.g.dart';

@JsonSerializable()
class Offline31 {
  const Offline31({required this.count, required this.amount});

  factory Offline31.fromJson(Map<String, Object?> json) =>
      _$Offline31FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Offline31ToJson(this);
}
