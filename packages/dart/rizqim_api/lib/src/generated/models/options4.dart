// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'providers.dart';

part 'options4.g.dart';

@JsonSerializable()
class Options4 {
  const Options4({
    required this.providers,
    required this.expiryMin,
    required this.expiryMax,
  });

  factory Options4.fromJson(Map<String, Object?> json) =>
      _$Options4FromJson(json);

  final List<Providers> providers;
  @JsonKey(name: 'expiry_min')
  final int expiryMin;
  @JsonKey(name: 'expiry_max')
  final int expiryMax;

  Map<String, Object?> toJson() => _$Options4ToJson(this);
}
