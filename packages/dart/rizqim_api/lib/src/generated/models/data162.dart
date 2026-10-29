// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'accounts2.dart';

part 'data162.g.dart';

@JsonSerializable()
class Data162 {
  const Data162({
    required this.totalDebt,
    required this.count,
    required this.accounts,
  });

  factory Data162.fromJson(Map<String, Object?> json) =>
      _$Data162FromJson(json);

  @JsonKey(name: 'total_debt')
  final String totalDebt;
  final int count;
  final List<Accounts2> accounts;

  Map<String, Object?> toJson() => _$Data162ToJson(this);
}
