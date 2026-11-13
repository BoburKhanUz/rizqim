// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'distributions2.dart';

part 'data137.g.dart';

@JsonSerializable()
class Data137 {
  const Data137({required this.poolPeriod, required this.distributions});

  factory Data137.fromJson(Map<String, Object?> json) =>
      _$Data137FromJson(json);

  @JsonKey(name: 'pool_period')
  final String poolPeriod;
  final List<Distributions2> distributions;

  Map<String, Object?> toJson() => _$Data137ToJson(this);
}
