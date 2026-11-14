// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entries9.dart';

part 'data279.g.dart';

@JsonSerializable()
class Data279 {
  const Data279({required this.account, required this.entries});

  factory Data279.fromJson(Map<String, Object?> json) =>
      _$Data279FromJson(json);

  final Account2 account;
  final List<Entries9> entries;

  Map<String, Object?> toJson() => _$Data279ToJson(this);
}
