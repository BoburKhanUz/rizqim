// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_method9.g.dart';

@JsonSerializable()
class ByMethod9 {
  const ByMethod9({
    required this.method,
    required this.count,
    required this.amount,
  });

  factory ByMethod9.fromJson(Map<String, Object?> json) =>
      _$ByMethod9FromJson(json);

  final String method;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$ByMethod9ToJson(this);
}
