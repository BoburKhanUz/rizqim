// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'preparing2.dart';
import 'ready2.dart';

part 'data233.g.dart';

@JsonSerializable()
class Data233 {
  const Data233({
    required this.businessDate,
    required this.preparing,
    required this.ready,
  });

  factory Data233.fromJson(Map<String, Object?> json) =>
      _$Data233FromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  final List<Preparing2> preparing;
  final List<Ready2> ready;

  Map<String, Object?> toJson() => _$Data233ToJson(this);
}
