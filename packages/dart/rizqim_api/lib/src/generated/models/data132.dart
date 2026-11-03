// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'distributions2.dart';

part 'data132.g.dart';

@JsonSerializable()
class Data132 {
  const Data132({required this.poolPeriod, required this.distributions});

  factory Data132.fromJson(Map<String, Object?> json) =>
      _$Data132FromJson(json);

  @JsonKey(name: 'pool_period')
  final String poolPeriod;
  final List<Distributions2> distributions;

  Map<String, Object?> toJson() => _$Data132ToJson(this);
}
