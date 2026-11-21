// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'continued5.g.dart';

@JsonSerializable()
class Continued5 {
  const Continued5({required this.count, required this.amount});

  factory Continued5.fromJson(Map<String, Object?> json) =>
      _$Continued5FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Continued5ToJson(this);
}
