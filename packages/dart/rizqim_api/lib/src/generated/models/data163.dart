// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'distributions2.dart';

part 'data163.g.dart';

@JsonSerializable()
class Data163 {
  const Data163({required this.poolPeriod, required this.distributions});

  factory Data163.fromJson(Map<String, Object?> json) =>
      _$Data163FromJson(json);

  @JsonKey(name: 'pool_period')
  final String poolPeriod;
  final List<Distributions2> distributions;

  Map<String, Object?> toJson() => _$Data163ToJson(this);
}
