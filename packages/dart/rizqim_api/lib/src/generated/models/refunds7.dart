// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method4.dart';

part 'refunds7.g.dart';

@JsonSerializable()
class Refunds7 {
  const Refunds7({
    required this.count,
    required this.amount,
    required this.byMethod,
  });

  factory Refunds7.fromJson(Map<String, Object?> json) =>
      _$Refunds7FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod4> byMethod;

  Map<String, Object?> toJson() => _$Refunds7ToJson(this);
}
