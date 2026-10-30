// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entry.dart';

part 'data213.g.dart';

@JsonSerializable()
class Data213 {
  const Data213({required this.account, required this.entry});

  factory Data213.fromJson(Map<String, Object?> json) =>
      _$Data213FromJson(json);

  final Account2 account;
  final Entry entry;

  Map<String, Object?> toJson() => _$Data213ToJson(this);
}
