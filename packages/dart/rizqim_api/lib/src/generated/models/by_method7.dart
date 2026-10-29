// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_method7.g.dart';

@JsonSerializable()
class ByMethod7 {
  const ByMethod7({
    required this.method,
    required this.count,
    required this.amount,
  });

  factory ByMethod7.fromJson(Map<String, Object?> json) =>
      _$ByMethod7FromJson(json);

  final String method;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$ByMethod7ToJson(this);
}
