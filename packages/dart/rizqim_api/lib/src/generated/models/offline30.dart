// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'offline30.g.dart';

@JsonSerializable()
class Offline30 {
  const Offline30({required this.count, required this.amount});

  factory Offline30.fromJson(Map<String, Object?> json) =>
      _$Offline30FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Offline30ToJson(this);
}
