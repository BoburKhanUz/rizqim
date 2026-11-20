// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'offline29.g.dart';

@JsonSerializable()
class Offline29 {
  const Offline29({required this.count, required this.amount});

  factory Offline29.fromJson(Map<String, Object?> json) =>
      _$Offline29FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Offline29ToJson(this);
}
