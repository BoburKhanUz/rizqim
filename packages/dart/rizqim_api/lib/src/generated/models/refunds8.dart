// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method5.dart';

part 'refunds8.g.dart';

@JsonSerializable()
class Refunds8 {
  const Refunds8({
    required this.count,
    required this.amount,
    required this.byMethod,
  });

  factory Refunds8.fromJson(Map<String, Object?> json) =>
      _$Refunds8FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod5> byMethod;

  Map<String, Object?> toJson() => _$Refunds8ToJson(this);
}
