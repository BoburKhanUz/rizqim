// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'preparing.dart';
import 'ready.dart';

part 'data290.g.dart';

@JsonSerializable()
class Data290 {
  const Data290({
    required this.businessDate,
    required this.preparing,
    required this.ready,
  });

  factory Data290.fromJson(Map<String, Object?> json) =>
      _$Data290FromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  final List<Preparing> preparing;
  final List<Ready> ready;

  Map<String, Object?> toJson() => _$Data290ToJson(this);
}
