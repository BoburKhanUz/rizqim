// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'entries2.dart';

part 'data140.g.dart';

@JsonSerializable()
class Data140 {
  const Data140({required this.account, required this.entries});

  factory Data140.fromJson(Map<String, Object?> json) =>
      _$Data140FromJson(json);

  final Account account;
  final List<Entries2> entries;

  Map<String, Object?> toJson() => _$Data140ToJson(this);
}
