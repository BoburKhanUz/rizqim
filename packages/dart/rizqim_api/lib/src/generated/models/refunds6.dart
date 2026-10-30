// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method6.dart';

part 'refunds6.g.dart';

@JsonSerializable()
class Refunds6 {
  const Refunds6({
    required this.count,
    required this.amount,
    required this.byMethod,
  });

  factory Refunds6.fromJson(Map<String, Object?> json) =>
      _$Refunds6FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod6> byMethod;

  Map<String, Object?> toJson() => _$Refunds6ToJson(this);
}
