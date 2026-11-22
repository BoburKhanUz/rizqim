// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'preparing2.dart';
import 'ready2.dart';

part 'data338.g.dart';

@JsonSerializable()
class Data338 {
  const Data338({
    required this.businessDate,
    required this.preparing,
    required this.ready,
  });

  factory Data338.fromJson(Map<String, Object?> json) =>
      _$Data338FromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  final List<Preparing2> preparing;
  final List<Ready2> ready;

  Map<String, Object?> toJson() => _$Data338ToJson(this);
}
