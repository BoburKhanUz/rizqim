// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'applied5.g.dart';

@JsonSerializable()
class Applied5 {
  const Applied5({required this.count, required this.amount});

  factory Applied5.fromJson(Map<String, Object?> json) =>
      _$Applied5FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Applied5ToJson(this);
}
