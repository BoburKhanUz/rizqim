// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'providers2.dart';

part 'options5.g.dart';

@JsonSerializable()
class Options5 {
  const Options5({
    required this.providers,
    required this.expiryMin,
    required this.expiryMax,
  });

  factory Options5.fromJson(Map<String, Object?> json) =>
      _$Options5FromJson(json);

  final List<Providers2> providers;
  @JsonKey(name: 'expiry_min')
  final int expiryMin;
  @JsonKey(name: 'expiry_max')
  final int expiryMax;

  Map<String, Object?> toJson() => _$Options5ToJson(this);
}
