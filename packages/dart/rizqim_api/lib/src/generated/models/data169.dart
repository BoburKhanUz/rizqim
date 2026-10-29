// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'preparing.dart';
import 'ready.dart';

part 'data169.g.dart';

@JsonSerializable()
class Data169 {
  const Data169({
    required this.businessDate,
    required this.preparing,
    required this.ready,
  });

  factory Data169.fromJson(Map<String, Object?> json) =>
      _$Data169FromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  final List<Preparing> preparing;
  final List<Ready> ready;

  Map<String, Object?> toJson() => _$Data169ToJson(this);
}
