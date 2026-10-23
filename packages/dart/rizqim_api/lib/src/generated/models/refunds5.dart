// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method2.dart';

part 'refunds5.g.dart';

@JsonSerializable()
class Refunds5 {
  const Refunds5({
    required this.count,
    required this.amount,
    required this.byMethod,
  });

  factory Refunds5.fromJson(Map<String, Object?> json) =>
      _$Refunds5FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod2> byMethod;

  Map<String, Object?> toJson() => _$Refunds5ToJson(this);
}
