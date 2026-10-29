// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method2.dart';

part 'refunds4.g.dart';

@JsonSerializable()
class Refunds4 {
  const Refunds4({
    required this.count,
    required this.amount,
    required this.byMethod,
  });

  factory Refunds4.fromJson(Map<String, Object?> json) =>
      _$Refunds4FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod2> byMethod;

  Map<String, Object?> toJson() => _$Refunds4ToJson(this);
}
