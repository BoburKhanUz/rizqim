// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_method4.g.dart';

@JsonSerializable()
class ByMethod4 {
  const ByMethod4({
    required this.method,
    required this.count,
    required this.amount,
  });

  factory ByMethod4.fromJson(Map<String, Object?> json) =>
      _$ByMethod4FromJson(json);

  final String method;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$ByMethod4ToJson(this);
}
