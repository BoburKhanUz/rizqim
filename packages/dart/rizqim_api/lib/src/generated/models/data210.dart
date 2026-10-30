// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'accounts2.dart';

part 'data210.g.dart';

@JsonSerializable()
class Data210 {
  const Data210({
    required this.totalDebt,
    required this.count,
    required this.accounts,
  });

  factory Data210.fromJson(Map<String, Object?> json) =>
      _$Data210FromJson(json);

  @JsonKey(name: 'total_debt')
  final String totalDebt;
  final int count;
  final List<Accounts2> accounts;

  Map<String, Object?> toJson() => _$Data210ToJson(this);
}
