// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method10.dart';

part 'refunds9.g.dart';

@JsonSerializable()
class Refunds9 {
  const Refunds9({
    required this.count,
    required this.amount,
    required this.byMethod,
  });

  factory Refunds9.fromJson(Map<String, Object?> json) =>
      _$Refunds9FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod10> byMethod;

  Map<String, Object?> toJson() => _$Refunds9ToJson(this);
}
